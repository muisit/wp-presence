import { api_list, api_misc } from "./api.js";
import { DataTable } from 'primereact/components/datatable/DataTable';
import { Column } from 'primereact/components/column/Column';

import GenericDialog from './dialogs/genericdialog';
import React from 'react';
import PagedTab from './pagedtab';
import { create_attributes_from_template } from './functions';

const fieldToSorterList={
    "id":"i",
    "name":"n",
    "modified": "m",
    "created": "c",
    "deleted": "d"
};

export default class GenericTab extends PagedTab {
    constructor(props, context) {
        super(props, context);
        this.abortType=this.props.template.name;
    }

    apiCall = (o,p,f,s) => {
        return api_list(this.abortType,'item',{ sort: s, offset: o, pagesize: p, filter: { 'all': true, type: this.props.template.name, name: f}, special: "include_3" });
    }

    fieldToSorter = (fld) => {
        if(fieldToSorterList[fld]) return fieldToSorterList[fld];
        return fld;
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
        var attrs=create_attributes_from_template(this.props.template.attributes);
        this.setState({item: {id:-1, type: this.props.template.name, state:'new',attributes:attrs},displayDialog:true});
    }

    onDownload = (event) => {
        var href = wppresence.url + "&model=" + this.props.template.name+'&nonce='+wppresence.nonce;
        console.log("opening ",href);
        window.open(href);
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

    renderAdd() {
        return (<span className="p-input-icon-left header-button">
            <a onClick={this.onAdd}><i className="pi pi-plus-circle">Add</i></a>&nbsp;|&nbsp;
            <a onClick={this.onDownload}><i className="pi pi-download">Download</i></a>&nbsp;&nbsp;
        </span>);
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
            <Column field="deleted" header="Deleted" sortable={true} />
            {this.props.template && this.props.template.attributes && this.props.template.attributes.map((a,idx) => {
                if(idx<3) {
                    return (<Column field={"a" + (idx+1)} header={a.name} sortable={true} key={idx}/>);
                }
                return undefined;
            })}
        </DataTable>);
    }
}
