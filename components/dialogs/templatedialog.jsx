import React from 'react';
import { api_misc, ap_misc } from "../api.js";
import { Button } from 'primereact/button';
import { Dialog } from 'primereact/dialog';
import { Dropdown } from 'primereact/dropdown';
import { Checkbox } from 'primereact/checkbox';
import { InputText } from 'primereact/inputtext';

export default class TemplateDialog extends React.Component {
    constructor(props, context) {
        super(props, context);
        this.state = {
            old_status:-1
        }
        this.abortType='template';
    }

    loading = (state) => {
        if(this.props.onLoad) this.props.onLoad(state);
    }

    close = () => {
        if(this.props.onClose) this.props.onClose();
    }

    save = (item) => {
        if(this.props.onSave) this.props.onSave(item);
        this.close();
    }

    delete = (item) => {
        if(this.props.onDelete) this.props.onDelete(item);
        this.close();
    }

    onCloseDialog = (event) => {
        this.loading(true);

        console.log('saving ',this.props.value);
        api_misc(this.abortType,'item','save',this.props.value)
            .then((json) => {
                this.loading(false);
                this.save(this.props.value);
            })
            .catch((err) => {
                console.log("caught error ",err);
                if(err.response.data.messages && err.response.data.messages.length) {
                    var txt="";
                    for(var i=0;i<err.response.data.messages.length;i++) {
                       txt+=err.response.data.messages[i]+"\r\n";
                    }
                    alert(txt);
                }
                else {
                    alert('Error storing the data. Please try again');
                }
            });
    }

    onCancelDialog = (event) => {
        this.close();
    }    

    onChangeEl = (event) => {
        if(!event.target) return;
        var item=Object.assign({},this.props.value);
        switch(event.target.name) {
        case 'name': item[event.target.name] = event.target.value; break;
        }
        if (this.props.onChange) this.props.onChange(item);
    }

    onAdd = () => {
        var newattrs=this.props.value.attributes.map((item)=>item);
        newattrs.push({name:'',value:'',id:-1,type:'string'});
        var item=this.props.value;
        item.attributes=newattrs;
        if (this.props.onChange) this.props.onChange(item);
    }

    onChangeAttr = (action, field,attr,newidx,value) => {
        console.log("onChangeAttr ",action,field,attr.name,value);
        console.log(this.props.value.attributes);
        var newattrs=null;
        if(action == 'delete' || action == 'set') {
            newattrs = this.props.value.attributes.map((item,idx) => {
                if(idx === newidx) {
                    if(action == 'delete') {
                        return null;
                    }
                    else {
                        var newitem=Object.assign({},item);
                        if(field == "groupBy") {
                            newitem['remark'] = Object.assign({},newitem['remarks']);
                            newitem['remark'].groupBy=value;
                        }
                        else {
                            newitem[field] = value;
                        }
                        return newitem;
                    }
                }
                else {
                    return Object.assign({},item);
                }
            });
            if(action === 'delete') {
                // filter out the potential null values
                newattrs = newattrs.filter(function(item) { return item !== null; });
            }
        }
        else if( (action == 'sortup' && newidx > 0) 
               || (action == 'sortdown' && newidx < (this.props.value.attributes.length-1))) {
            newattrs=this.props.value.attributes.slice();
            if(action == "sortup") {
                // reinsert the item at idx - 1
                var item = newattrs.splice(newidx,1);
                newattrs.splice(newidx-1,0,item[0]);
            }
            else {
                // reinsert the item at idx+1, because we insert after
                // the current item
                var item = newattrs.splice(newidx,1);
                newattrs.splice(newidx+1,0,item[0]);
            }
        }
        if(newattrs !== null) {
            var item=this.props.value;
            item.attributes=newattrs;
            console.log("attributes are now ",newattrs);
            if (this.props.onChange) this.props.onChange(item);
        }
    }

    onDeleteDialog = (event) => {
        if(confirm('Are you sure you want to delete template '+ this.props.value.name + "? This action cannot be undone!")) {
            this.loading(true);
            api_misc(this.abortType,'item','delete',{ id: this.props.value.id})
            .then((json) => {
                this.loading(false);
                this.delete();
            })
            .catch((err) => {
                if(err.response.data.messages && err.response.data.messages.length) {
                    var txt="";
                    for(var i=0;i<err.response.data.messages.length;i++) {
                        txt+=err.response.data.messages[i]+"\r\n";
                    }
                    alert(txt);
                }
                else {
                    alert('Error removing the data. Please try again');
                }
            })
    
        }
    }

    render() {
        var footer=(<div>
        <Button label="Add" icon="pi pi-plus" className="p-button-raised p-button-text" onClick={this.onAdd} />
        <Button label="Cancel" icon="pi pi-times" className="p-button-warning p-button-raised p-button-text" onClick={this.onCancelDialog} />
        <Button label="Save" icon="pi pi-check" className="p-button-raised" onClick={this.onCloseDialog} />
</div>);
        if(this.props.value.id >0) {
            footer=(<div>
                <Button label="Add" icon="pi pi-plus" className="p-button-raised p-button-text" onClick={this.onAdd} />
                <Button label="Remove" icon="pi pi-trash" className="p-button-danger p-button-raised p-button-text" onClick={this.onDeleteDialog} />
                <Button label="Cancel" icon="pi pi-times" className="p-button-warning p-button-raised p-button-text" onClick={this.onCancelDialog} />
                <Button label="Save" icon="pi pi-check" className="p-button-raised" onClick={this.onCloseDialog} />
</div>);
        }

        const types=[
            {name: 'Text', value: 'string'},
            {name: 'Number', value: 'number'},
            {name: 'Integer', value: 'int'},
            {name: 'Year', value: 'year'}, // four digit value
            {name: 'Date', value: 'date' }, // ISO field
            {name: 'Enum', value: 'enum' }, // enumeration
        ];

        var lastattr=this.props.value.attributes ? this.props.value.attributes.length : 0;
        return (<Dialog header="Edit Template" position="center" visible={this.props.display} style={{ width: '50vw' }} modal={true} footer={footer} onHide={this.onCancelDialog}>
      <div>
        <label>Name</label>
        <div className='input'>
            <InputText name='name' value={this.props.value.name} onChange={this.onChangeEl} placeholder='Name'/>
        </div>
      </div>
      {this.props.value.attributes && this.props.value.attributes.map((attr,idx) => (
          <div className='attribute' key={idx}>
          <label>Attribute: <InputText name={'name'+idx} value={attr.name} onChange={(e) => this.onChangeAttr('set','name', attr,idx,e.target.value)} placeholder='Name'/></label>
          <div className='inputs'>
            <div className='input'>
              <Dropdown appendTo={document.body} name={'type'+idx} optionLabel="name" optionValue="value" value={attr.type} options={types} placeholder="Type" onChange={(e) => this.onChangeAttr('set', 'type', attr,idx,e.target.value)}/>            
            </div>
            <div className='input'>
              <InputText name={'default'+idx} value={attr.value} onChange={(e) => this.onChangeAttr('set','value', attr,idx,e.target.value)} placeholder='Default'/>
            </div>
            <div className='input'>
              <Checkbox inputId={'group'+idx} checked={attr.remark && attr.remark.groupBy} onChange={(e) => this.onChangeAttr('set','groupBy', attr,idx,e.checked)}/>
              <label htmlFor={'group'+idx}>Grp</label>
            </div>
            <div className='actions'>
              <i className="pi pi-trash pi-mr-2" onClick={(e) => this.onChangeAttr('delete','', attr,idx,'')}></i>
              <i className="pi pi-angle-up pi-mr-2" onClick={(e) => this.onChangeAttr('sortup','', attr,idx,'')}></i>
              <i className="pi pi-angle-down pi-mr-2" onClick={(e) => this.onChangeAttr('sortdown','', attr,idx,'')}></i>
            </div>
          </div>
        </div>
      ))}
</Dialog>
);
    }
}

