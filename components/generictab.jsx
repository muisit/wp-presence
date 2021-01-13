import { api_list, api_misc } from "./api.js";
import { DataTable } from 'primereact/components/datatable/DataTable';
import { Column } from 'primereact/components/column/Column';

import GenericDialog from './dialogs/genericdialog';
import React from 'react';
import PagedTab from './pagedtab';

const fieldToSorterList={
    "id":"i",
    "name":"n"
};

export default class GenericTab extends PagedTab {
    constructor(props, context) {
        super(props, context);
        this.abortType=this.props.template.name;
    }

    apiCall = (o,p,f,s) => {
        return api_list(this.abortType,'item',{ sort: s, offset: o, pagesize: p, filter: { type: this.props.template.name, name: f}, special: "include_3" });
    }

    fieldToSorter = (fld) => {
        return fieldToSorterList[fld];
    }

    toastMessage = (type,item) => {
        if(type == "save") {
            return { severity: 'info', summary: 'Item Saved', detail: 'Item ' + item.name+ ' was succesfully stored in the database', life: 3000 };
        }
        if(type == "delete") {
            return { severity: 'info', summary: 'Item Deleted', detail: 'Item ' + item.name + ' was succesfully removed from the database', life: 3000 };
        }
        return {"severity":"info","summary":"Unknown","detail":"Unknown event for "+JSON.stringify(type) + " and " + JSON.stringify(item),"life":3000};
    }

    onAdd = (event) => {
        var attrs=this.props.template.attributes.map((item) => {
            var def=item.value;
            if(item.type === 'enum') {
                def=item.value.split(' ')[0];
            }
            else if(item.type == 'int') {
                def=0;
            }
            else if(item.type =='year') {
                def=2000;
            }
            else if(item.type == 'number') {
                def=0.0;
            }
            return {
                id: -1,
                name: item.name,
                type: item.type,
                value: def
            }
        });
        this.setState({item: {id:-1, type: this.props.template.name, state:'new',attributes:attrs},displayDialog:true});
    }

    onEdit = (event)=> {
        var item = Object.assign({},event.data);
        api_list(this.abortType,"eva",{filter: { item_id: item.id}})
            .then((res) => {
                if(res.data.list) {
                    item.attributes = res.data.list;
                    this.setState({item: item, displayDialog:true });
                }
            });
        return false;
    }

    renderDialog() {
        return (
            <GenericDialog onClose={this.onClose} onChange={this.onChange} onSave={this.onSave} onDelete={this.onDelete} onLoad={this.onLoad} display={this.state.displayDialog} value={this.state.item}  template={this.props.template} />
        );
    }

    renderTable(pager) {
        return (<DataTable
          ref={this.dt}
          value={this.state.items}
          className="p-datatable-striped"
          lazy={true} onPage={this.onLazyLoad} loading={this.state.loading}
          paginator={false}
          header={pager}
          footer={pager}
          sortMode="multiple" multiSortMeta={this.state.multiSortMeta} onSort={this.onSort}
          onRowDoubleClick={this.onEdit}
          >
            <Column field="id" header="ID" sortable={true} />
            <Column field="name" header="Name" sortable={true}/>
            <Column field="created" header="Created" sortable={true}/>
            <Column field="modified" header="Modified" sortable={true}/>
            <Column field="state" header="State" sortable={true}/>
            {this.props.template && this.props.template.attributes && this.props.template.attributes.map((a,idx) => {
                if(idx<3) {
                    return (<Column field={"a" + (idx+1)} header={a.name} sortable={true} key={idx}/>);
                }
                return undefined;
            })}
        </DataTable>);
    }
}
